<?php
/**
 * PunktePass Standalone Admin - Megújítások & Filiálé kérések
 * Route: /admin/renewals
 * Handles: Subscription renewals + Branch (filiale) expansion requests
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Renewals {

    /**
     * Render renewals page
     */
    public static function render() {
        global $wpdb;

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['mark_done'])) {
                self::handle_mark_done();
            } elseif (isset($_POST['filiale_action'])) {
                self::handle_filiale_action();
            }
        }

        // Get current tab
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'subscription';

        // Fetch subscription renewal requests
        $renewal_requests = $wpdb->get_results("
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

        // Fetch filiale requests
        $filiale_requests = $wpdb->get_results("
            SELECT
                fr.*,
                s.name as store_name,
                s.company_name,
                s.email as store_email,
                s.phone as store_phone,
                s.city,
                s.max_filialen,
                (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE parent_store_id = fr.store_id OR id = fr.store_id) as current_count
            FROM {$wpdb->prefix}ppv_filiale_requests fr
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON fr.store_id = s.id
            WHERE fr.status = 'pending'
            ORDER BY fr.created_at DESC
        ");

        $renewal_count = count($renewal_requests);
        $filiale_count = count($filiale_requests);

        self::render_html($renewal_requests, $filiale_requests, $renewal_count, $filiale_count, $tab);
    }

    /**
     * Handle mark as done action for subscription renewals
     */
    private static function handle_mark_done() {
        global $wpdb;

        $handler_id = intval($_POST['handler_id'] ?? 0);
        if ($handler_id > 0) {
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
            ppv_log("✅ [Renewals Admin] Renewal request marked as done for Handler #{$handler_id}");
        }

        wp_redirect('/admin/renewals?tab=subscription&success=marked_done');
        exit;
    }

    /**
     * Handle filiale request actions (approve/reject/modify)
     */
    private static function handle_filiale_action() {
        global $wpdb;

        $request_id = intval($_POST['request_id'] ?? 0);
        $action = sanitize_text_field($_POST['filiale_action'] ?? '');
        $new_max = intval($_POST['new_max'] ?? 0);
        $admin_note = sanitize_textarea_field($_POST['admin_note'] ?? '');

        if ($request_id <= 0) {
            wp_redirect('/admin/renewals?tab=filiale&error=invalid_request');
            exit;
        }

        // Get request info
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT fr.*, s.name, s.company_name, s.email, s.max_filialen
             FROM {$wpdb->prefix}ppv_filiale_requests fr
             LEFT JOIN {$wpdb->prefix}ppv_stores s ON fr.store_id = s.id
             WHERE fr.id = %d",
            $request_id
        ));

        if (!$request) {
            wp_redirect('/admin/renewals?tab=filiale&error=not_found');
            exit;
        }

        if ($action === 'approve') {
            // Calculate new max (current + requested or custom value)
            $final_max = $new_max > 0 ? $new_max : ($request->max_filialen + $request->requested_amount);

            // Update store's max_filialen
            $wpdb->update(
                "{$wpdb->prefix}ppv_stores",
                ['max_filialen' => $final_max],
                ['id' => $request->store_id],
                ['%d'],
                ['%d']
            );

            // Update request status
            $wpdb->update(
                "{$wpdb->prefix}ppv_filiale_requests",
                [
                    'status' => 'approved',
                    'admin_note' => $admin_note,
                    'processed_at' => current_time('mysql')
                ],
                ['id' => $request_id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            // Send confirmation email to handler
            self::send_filiale_email($request, 'approved', $final_max, $admin_note);

            ppv_log("✅ [Renewals Admin] Filiale request #{$request_id} APPROVED - Store #{$request->store_id} max_filialen: {$final_max}");
            wp_redirect('/admin/renewals?tab=filiale&success=approved');

        } elseif ($action === 'reject') {
            // Update request status
            $wpdb->update(
                "{$wpdb->prefix}ppv_filiale_requests",
                [
                    'status' => 'rejected',
                    'admin_note' => $admin_note,
                    'processed_at' => current_time('mysql')
                ],
                ['id' => $request_id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            // Send rejection email to handler
            self::send_filiale_email($request, 'rejected', 0, $admin_note);

            ppv_log("❌ [Renewals Admin] Filiale request #{$request_id} REJECTED - Store #{$request->store_id}");
            wp_redirect('/admin/renewals?tab=filiale&success=rejected');

        } elseif ($action === 'modify') {
            // Just modify max_filialen without request
            if ($new_max > 0) {
                $wpdb->update(
                    "{$wpdb->prefix}ppv_stores",
                    ['max_filialen' => $new_max],
                    ['id' => $request->store_id],
                    ['%d'],
                    ['%d']
                );

                // Mark request as approved
                $wpdb->update(
                    "{$wpdb->prefix}ppv_filiale_requests",
                    [
                        'status' => 'approved',
                        'admin_note' => "Max auf {$new_max} gesetzt. " . $admin_note,
                        'processed_at' => current_time('mysql')
                    ],
                    ['id' => $request_id],
                    ['%s', '%s', '%s'],
                    ['%d']
                );

                self::send_filiale_email($request, 'approved', $new_max, $admin_note);

                ppv_log("✏️ [Renewals Admin] Filiale request #{$request_id} MODIFIED - Store #{$request->store_id} max_filialen: {$new_max}");
                wp_redirect('/admin/renewals?tab=filiale&success=modified');
            } else {
                wp_redirect('/admin/renewals?tab=filiale&error=invalid_max');
            }
        }

        exit;
    }

    /**
     * Send email notification to handler about filiale request result
     */
    private static function send_filiale_email($request, $status, $new_max, $admin_note) {
        $to = $request->contact_email ?: $request->email;
        $store_name = $request->company_name ?: $request->name;

        if ($status === 'approved') {
            $subject = "✅ Filialen-Anfrage genehmigt - {$store_name}";
            $body = "Guten Tag,\n\n";
            $body .= "Ihre Anfrage für zusätzliche Filialen wurde genehmigt!\n\n";
            $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $body .= "Neues Filialen-Limit: {$new_max}\n";
            $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $body .= "Sie können jetzt neue Filialen in Ihrem PunktePass-Konto hinzufügen.\n\n";
            if (!empty($admin_note)) {
                $body .= "Hinweis: {$admin_note}\n\n";
            }
        } else {
            $subject = "❌ Filialen-Anfrage abgelehnt - {$store_name}";
            $body = "Guten Tag,\n\n";
            $body .= "Leider können wir Ihre Anfrage für zusätzliche Filialen derzeit nicht genehmigen.\n\n";
            if (!empty($admin_note)) {
                $body .= "Begründung: {$admin_note}\n\n";
            }
            $body .= "Bei Fragen können Sie uns jederzeit kontaktieren.\n\n";
        }

        $body .= "Mit freundlichen Grüßen,\n";
        $body .= "Ihr PunktePass-Team\n\n";
        $body .= "www.punktepass.de";

        $headers = [
            'From: PunktePass <info@punktepass.de>',
            'Reply-To: info@punktepass.de',
            'Content-Type: text/plain; charset=UTF-8'
        ];

        wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Render HTML
     */
    private static function render_html($renewal_requests, $filiale_requests, $renewal_count, $filiale_count, $current_tab) {
        $success = isset($_GET['success']) ? $_GET['success'] : '';
        $error = isset($_GET['error']) ? $_GET['error'] : '';
        $total_count = $renewal_count + $filiale_count;
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Megújítások & Filiálék - PunktePass Admin</title>
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
                .success-msg {
                    background: #0f5132;
                    color: #d1e7dd;
                    padding: 12px 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }
                .error-msg {
                    background: #842029;
                    color: #f8d7da;
                    padding: 12px 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }
                .tabs {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 20px;
                }
                .tab {
                    padding: 12px 24px;
                    background: #16213e;
                    border-radius: 8px;
                    text-decoration: none;
                    color: #aaa;
                    font-weight: 600;
                    transition: all 0.2s;
                    position: relative;
                }
                .tab:hover { background: #1f2b4d; color: #fff; }
                .tab.active { background: #00d9ff; color: #000; }
                .tab .badge {
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    background: #f44336;
                    color: #fff;
                    font-size: 11px;
                    min-width: 20px;
                    height: 20px;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0 6px;
                }
                .empty-state {
                    text-align: center;
                    padding: 60px 20px;
                    background: #16213e;
                    border-radius: 12px;
                    color: #888;
                }
                .empty-state i { font-size: 48px; margin-bottom: 15px; display: block; color: #00d9ff; }
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
                .badge-status {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 11px;
                    font-weight: 600;
                }
                .badge-success { background: #0f5132; color: #d1e7dd; }
                .badge-info { background: #084298; color: #cfe2ff; }
                .badge-warning { background: #664d03; color: #fff3cd; }
                .badge-error { background: #842029; color: #f8d7da; }
                .badge-purple { background: #5b21b6; color: #ede9fe; }
                .btn {
                    display: inline-block;
                    padding: 6px 12px;
                    border-radius: 6px;
                    font-size: 12px;
                    font-weight: 600;
                    text-decoration: none;
                    cursor: pointer;
                    border: none;
                    transition: all 0.2s;
                    margin: 2px;
                }
                .btn-success { background: #10b981; color: #fff; }
                .btn-success:hover { background: #059669; }
                .btn-danger { background: #ef4444; color: #fff; }
                .btn-danger:hover { background: #dc2626; }
                .btn-primary { background: #00d9ff; color: #000; }
                .btn-primary:hover { background: #00b8d9; }
                .btn-secondary { background: #374151; color: #fff; }
                .btn-secondary:hover { background: #4b5563; }
                a { color: #00d9ff; text-decoration: none; }
                a:hover { text-decoration: underline; }
                .phone-highlight { color: #00d9ff; font-weight: 600; }
                .filiale-info {
                    background: #0f3460;
                    padding: 8px 12px;
                    border-radius: 6px;
                    display: inline-block;
                }
                .filiale-current { color: #fbbf24; }
                .filiale-max { color: #10b981; }
                .filiale-requested { color: #f472b6; }

                /* Modal */
                .modal-overlay {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.8);
                    z-index: 1000;
                    justify-content: center;
                    align-items: center;
                    padding: 20px;
                }
                .modal-overlay.active { display: flex; }
                .modal {
                    background: #16213e;
                    border-radius: 16px;
                    width: 100%;
                    max-width: 500px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
                }
                .modal-header {
                    padding: 20px;
                    border-bottom: 1px solid #0f3460;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .modal-header h2 { font-size: 18px; color: #00d9ff; }
                .modal-close {
                    background: none;
                    border: none;
                    color: #888;
                    font-size: 24px;
                    cursor: pointer;
                }
                .modal-close:hover { color: #fff; }
                .modal-body { padding: 20px; }
                .form-group { margin-bottom: 15px; }
                .form-group label {
                    display: block;
                    font-size: 12px;
                    color: #888;
                    margin-bottom: 5px;
                    text-transform: uppercase;
                }
                .form-group input, .form-group textarea, .form-group select {
                    width: 100%;
                    padding: 10px 12px;
                    background: #0a1628;
                    border: 1px solid #1f2b4d;
                    border-radius: 6px;
                    color: #fff;
                    font-size: 14px;
                }
                .form-group input:focus, .form-group textarea:focus {
                    outline: none;
                    border-color: #00d9ff;
                }
                .modal-actions {
                    display: flex;
                    gap: 10px;
                    margin-top: 20px;
                }
                .info-box {
                    background: #0a1628;
                    padding: 15px;
                    border-radius: 8px;
                    margin-bottom: 15px;
                    font-size: 13px;
                    line-height: 1.6;
                }
            </style>
        </head>
        <body>
            <div class="admin-header">
                <h1>📋 Kérelmek (<?php echo $total_count; ?> nyitott)</h1>
                <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Vissza</a>
            </div>

            <div class="container">
                <?php if ($success === 'marked_done'): ?>
                    <div class="success-msg">✅ Előfizetés kérelem készként jelölve!</div>
                <?php elseif ($success === 'approved'): ?>
                    <div class="success-msg">✅ Filiálé kérés jóváhagyva! Az új limit beállítva.</div>
                <?php elseif ($success === 'rejected'): ?>
                    <div class="success-msg">❌ Filiálé kérés elutasítva. Email elküldve a handlernek.</div>
                <?php elseif ($success === 'modified'): ?>
                    <div class="success-msg">✏️ Filiálé limit módosítva!</div>
                <?php endif; ?>

                <?php if ($error === 'invalid_max'): ?>
                    <div class="error-msg">Hiba: Érvénytelen filiálé limit!</div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="tabs">
                    <a href="/admin/renewals?tab=subscription" class="tab <?php echo $current_tab === 'subscription' ? 'active' : ''; ?>">
                        <i class="ri-refresh-line"></i> Előfizetés
                        <?php if ($renewal_count > 0): ?><span class="badge"><?php echo $renewal_count; ?></span><?php endif; ?>
                    </a>
                    <a href="/admin/renewals?tab=filiale" class="tab <?php echo $current_tab === 'filiale' ? 'active' : ''; ?>">
                        <i class="ri-store-2-line"></i> Filiálé
                        <?php if ($filiale_count > 0): ?><span class="badge"><?php echo $filiale_count; ?></span><?php endif; ?>
                    </a>
                </div>

                <?php if ($current_tab === 'subscription'): ?>
                    <!-- Subscription Renewals Tab -->
                    <?php if ($renewal_count === 0): ?>
                        <div class="empty-state">
                            <i class="ri-checkbox-circle-line"></i>
                            <h3>Nincs nyitott előfizetés kérelem!</h3>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cégnév</th>
                                    <th>E-mail</th>
                                    <th>Telefon</th>
                                    <th>Megújítási telefon</th>
                                    <th>Város</th>
                                    <th>Kérelem</th>
                                    <th>Státusz</th>
                                    <th>Műveletek</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($renewal_requests as $request): ?>
                                    <?php
                                    $now = current_time('timestamp');
                                    $trial_end = !empty($request->trial_ends_at) ? strtotime($request->trial_ends_at) : 0;
                                    $sub_end = !empty($request->subscription_expires_at) ? strtotime($request->subscription_expires_at) : 0;

                                    $trial_days_left = $trial_end > 0 ? max(0, ceil(($trial_end - $now) / 86400)) : 0;
                                    $sub_days_left = $sub_end > 0 ? max(0, ceil(($sub_end - $now) / 86400)) : 0;

                                    if ($request->subscription_status === 'active') {
                                        $status_text = $sub_days_left > 0 ? "✅ Aktív ({$sub_days_left} nap)" : '❌ Lejárt';
                                        $status_class = $sub_days_left > 0 ? 'success' : 'error';
                                    } else {
                                        $status_text = $trial_days_left > 0 ? "📅 Próba ({$trial_days_left} nap)" : '❌ Lejárt';
                                        $status_class = $trial_days_left > 0 ? 'info' : 'error';
                                    }

                                    $requested_time = date('Y-m-d H:i', strtotime($request->subscription_renewal_requested));
                                    ?>
                                    <tr>
                                        <td><?php echo intval($request->id); ?></td>
                                        <td><strong><?php echo esc_html($request->company_name ?: $request->name); ?></strong></td>
                                        <td><a href="mailto:<?php echo esc_attr($request->email); ?>"><?php echo esc_html($request->email); ?></a></td>
                                        <td>
                                            <?php if (!empty($request->phone)): ?>
                                                <a href="tel:<?php echo esc_attr($request->phone); ?>"><?php echo esc_html($request->phone); ?></a>
                                            <?php else: ?>-<?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($request->renewal_phone)): ?>
                                                <span class="phone-highlight">📞 <?php echo esc_html($request->renewal_phone); ?></span>
                                            <?php else: ?>-<?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($request->city); ?></td>
                                        <td><?php echo $requested_time; ?></td>
                                        <td><span class="badge-status badge-<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                        <td>
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="mark_done" value="1">
                                                <input type="hidden" name="handler_id" value="<?php echo intval($request->id); ?>">
                                                <button type="submit" class="btn btn-primary" onclick="return confirm('Készként jelöli?');">✅ Kész</button>
                                            </form>
                                            <a href="mailto:<?php echo esc_attr($request->email); ?>?subject=Előfizetés%20hosszabbítás" class="btn btn-secondary">📧</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Filiale Requests Tab -->
                    <?php if ($filiale_count === 0): ?>
                        <div class="empty-state">
                            <i class="ri-store-2-line"></i>
                            <h3>Nincs nyitott filiálé kérelem!</h3>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Cégnév</th>
                                    <th>Filiálék</th>
                                    <th>Kapcsolat</th>
                                    <th>Üzenet</th>
                                    <th>Kérelem</th>
                                    <th>Műveletek</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filiale_requests as $request): ?>
                                    <?php
                                    $requested_time = date('Y-m-d H:i', strtotime($request->created_at));
                                    $new_max_suggested = $request->max_filialen + $request->requested_amount;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo intval($request->id); ?></strong>
                                            <br><small style="color:#888;">Store #<?php echo intval($request->store_id); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($request->company_name ?: $request->store_name); ?></strong>
                                            <br><small style="color:#888;"><?php echo esc_html($request->city); ?></small>
                                        </td>
                                        <td>
                                            <div class="filiale-info">
                                                <span class="filiale-current"><?php echo intval($request->current_count); ?></span> /
                                                <span class="filiale-max"><?php echo intval($request->max_filialen); ?></span>
                                                <br>
                                                <small class="filiale-requested">+<?php echo intval($request->requested_amount); ?> kért</small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($request->contact_email)): ?>
                                                <a href="mailto:<?php echo esc_attr($request->contact_email); ?>">📧 <?php echo esc_html($request->contact_email); ?></a><br>
                                            <?php endif; ?>
                                            <?php if (!empty($request->contact_phone)): ?>
                                                <a href="tel:<?php echo esc_attr($request->contact_phone); ?>">📞 <?php echo esc_html($request->contact_phone); ?></a>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 200px;">
                                            <small><?php echo esc_html(mb_substr($request->message ?: '-', 0, 100)); ?></small>
                                        </td>
                                        <td><?php echo $requested_time; ?></td>
                                        <td>
                                            <button class="btn btn-success" onclick="openFilialeModal(<?php echo intval($request->id); ?>, 'approve', <?php echo intval($request->store_id); ?>, <?php echo intval($request->max_filialen); ?>, <?php echo intval($request->requested_amount); ?>, '<?php echo esc_js($request->company_name ?: $request->store_name); ?>')">
                                                ✅ Jóváhagy
                                            </button>
                                            <button class="btn btn-danger" onclick="openFilialeModal(<?php echo intval($request->id); ?>, 'reject', <?php echo intval($request->store_id); ?>, <?php echo intval($request->max_filialen); ?>, <?php echo intval($request->requested_amount); ?>, '<?php echo esc_js($request->company_name ?: $request->store_name); ?>')">
                                                ❌ Elutasít
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Filiale Action Modal -->
            <div class="modal-overlay" id="filialeModal">
                <div class="modal">
                    <div class="modal-header">
                        <h2 id="modal-title">Filiálé kérés kezelése</h2>
                        <button class="modal-close" onclick="closeFilialeModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="post" id="filiale-form">
                            <input type="hidden" name="request_id" id="modal-request-id" value="">
                            <input type="hidden" name="filiale_action" id="modal-action" value="">

                            <div class="info-box" id="modal-info"></div>

                            <div class="form-group" id="new-max-group">
                                <label>Új filiálé limit</label>
                                <input type="number" name="new_max" id="modal-new-max" min="1" max="50">
                                <small style="color:#888;">Hagyd üresen az automatikus számításhoz</small>
                            </div>

                            <div class="form-group">
                                <label>Admin megjegyzés (opcionális)</label>
                                <textarea name="admin_note" id="modal-admin-note" rows="3" placeholder="Megjegyzés a handlernek..."></textarea>
                            </div>

                            <div class="modal-actions">
                                <button type="submit" class="btn btn-success" id="modal-submit-btn">Mentés</button>
                                <button type="button" class="btn btn-secondary" onclick="closeFilialeModal()">Mégse</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
            function openFilialeModal(requestId, action, storeId, currentMax, requestedAmount, storeName) {
                document.getElementById('modal-request-id').value = requestId;
                document.getElementById('modal-action').value = action;

                const newMaxSuggested = currentMax + requestedAmount;

                if (action === 'approve') {
                    document.getElementById('modal-title').textContent = '✅ Filiálé kérés jóváhagyása';
                    document.getElementById('modal-info').innerHTML =
                        '<strong>' + storeName + '</strong><br>' +
                        'Jelenlegi limit: ' + currentMax + '<br>' +
                        'Kért mennyiség: +' + requestedAmount + '<br>' +
                        '<strong>Javasolt új limit: ' + newMaxSuggested + '</strong>';
                    document.getElementById('modal-new-max').value = newMaxSuggested;
                    document.getElementById('new-max-group').style.display = 'block';
                    document.getElementById('modal-submit-btn').textContent = '✅ Jóváhagyás';
                    document.getElementById('modal-submit-btn').className = 'btn btn-success';
                } else {
                    document.getElementById('modal-title').textContent = '❌ Filiálé kérés elutasítása';
                    document.getElementById('modal-info').innerHTML =
                        '<strong>' + storeName + '</strong><br>' +
                        'Jelenlegi limit: ' + currentMax + '<br>' +
                        'Kért mennyiség: +' + requestedAmount + '<br>' +
                        '<strong style="color:#ef4444;">A kérés el lesz utasítva</strong>';
                    document.getElementById('modal-new-max').value = '';
                    document.getElementById('new-max-group').style.display = 'none';
                    document.getElementById('modal-submit-btn').textContent = '❌ Elutasítás';
                    document.getElementById('modal-submit-btn').className = 'btn btn-danger';
                }

                document.getElementById('modal-admin-note').value = '';
                document.getElementById('filialeModal').classList.add('active');
            }

            function closeFilialeModal() {
                document.getElementById('filialeModal').classList.remove('active');
            }

            document.getElementById('filialeModal').addEventListener('click', function(e) {
                if (e.target === this) closeFilialeModal();
            });
            </script>
        </body>
        </html>
        <?php
    }
}
